/* Play Space Finanças 2.1: as Planilhas (pedido do dono: "uma aba planilha: importo uma planilha modelo, o sistema lê, copia tudo o que
   foi escrito e os próximos campos ela alimenta direto do sistema; planilha 1, planilha 2, planilha 3; a planilha não precisa aparecer,
   só um botão importar, e o sistema põe os campos bonitinhos no mesmo visual do sistema").
   - Importar: o arquivo (.xlsx ou .csv) é lido aqui mesmo. Em cada aba, o sistema acha o cabeçalho (a linha com os nomes das colunas,
     mesmo com título em cima), as colunas e o tipo de cada uma (data, valor em R$, número, lista de opções, texto), e copia as linhas
     (as vazias e as de total ficam fora: o sistema soma sozinho). Antes de importar, mostra como vai ficar (dá para mudar o nome e o tipo).
   - Cada planilha vira uma tela do sistema: busca, filtro por mês (se tiver data), ordenar pela coluna, o total das colunas de valor;
     "+ Nova linha" abre os campos certos de cada coluna; tocar numa linha corrige ou tira (fica quem lançou e quem corrigiu).
   - Colunas: renomear, mudar o tipo, esconder, mudar a ordem, pôr uma coluna nova. Importar mais linhas (as colunas pelo nome).
   - Exportar em Excel, no mesmo formato. O arquivo importado não fica guardado: o que fica é a planilha dentro do sistema. */
'use strict';
const PL = { lista: null, id: 0, atual: null, busca: '', mes: '', ordem: null, mostra: 300 };
const PL_TIPOS = { texto: 'Texto', data: 'Data', valor: 'Valor (R$)', numero: 'Número', lista: 'Lista de opções' };
const PL_ICONE = { texto: 'Aa', data: '📅', valor: 'R$', numero: '#', lista: '☰' };

function planDesenhar() {
  const t = $('#tela'); if (!t) return;
  if (PL.id) { t.innerHTML = telaPlanilha(); ligarPlanilha(); if (!PL.atual || PL.atual.planilha.id !== PL.id) plCarregar(); return; }
  t.innerHTML = telaPlanilhas(); ligarPlanilhas(); if (!PL.lista) plCarregarLista();
}
async function plCarregarLista() { const n = ++GS.n; try { const r = await api('pl_listar'); if (n !== GS.n) return; PL.lista = r.planilhas; } catch (e) { aviso(e.message); return; } if (ABA === 'plan' && !PL.id) planDesenhar(); }
async function plCarregar() { const n = ++GS.n, id = PL.id; try { const r = await api('pl_ver', { id }); if (n !== GS.n || PL.id !== id) return; PL.atual = r; } catch (e) { aviso(e.message); PL.id = 0; PL.lista = null; } if (ABA === 'plan') planDesenhar(); }
function plAbre(id) { PL.id = id; PL.atual = null; PL.busca = ''; PL.mes = ''; PL.ordem = null; PL.mostra = 300; planDesenhar(); window.scrollTo(0, 0); }

/* ---------- a lista das planilhas ---------- */
function telaPlanilhas() {
  const L = PL.lista;
  const cab = `<div class="topo"><div><h1>Planilhas</h1><div class="sub">Importe uma planilha modelo: o sistema lê, copia o que está escrito e as próximas linhas vocês lançam aqui</div></div>
      <div class="lista-acoes"><button class="btn btn-brand g-novo" data-pl="importar">Importar planilha</button></div></div>`;
  if (!L) return cab + '<div class="vazio">Carregando…</div>';
  const vazio = `<div class="cartao pl-vazio"><div class="pl-passos"><div><b>1</b>Toque em <b>Importar planilha</b> e escolha o arquivo do Excel (.xlsx) ou .csv.</div><div><b>2</b>O sistema acha as colunas (data, valor, texto, lista…) e mostra como vai ficar.</div><div><b>3</b>Pronto: a planilha vira uma tela do sistema. As próximas linhas vocês lançam aqui, sem Excel.</div></div>
      <button class="btn btn-brand" style="max-width:280px" data-pl="importar">Importar planilha</button><p class="sub" style="margin-top:14px">A planilha do financeiro (Faturamento, Despesas, Recebimentos e Rede) entra por <b>Lançamentos → Importar a planilha</b>: lá ela vira lançamentos, com o relatório e os gráficos.</p></div>`;
  if (!L.length) return cab + vazio;
  return cab + `<div class="pl-cards">${L.map(p => { const vis = p.colunas.filter(c => !c.oculta); return `<button class="pl-card" data-pla="${p.id}"><span class="pl-ic">${IC.plan}</span><b>${esc(p.nome)}</b>
      <span class="sub">${int(p.n)} ${p.n === 1 ? 'linha' : 'linhas'} · ${int(vis.length)} ${vis.length === 1 ? 'coluna' : 'colunas'}</span><span class="pl-cols">${vis.slice(0, 6).map(c => `<i>${esc(c.nome)}</i>`).join('')}${vis.length > 6 ? `<i>+${vis.length - 6}</i>` : ''}</span>
      <span class="sub pl-quando">${p.ult ? 'Atualizada em ' + esc(quandoBR(p.ult)) : ''}</span></button>`; }).join('')}
    <button class="pl-card pl-mais" data-pl="importar"><span class="pl-ic">+</span><b>Importar outra planilha</b><span class="sub">.xlsx ou .csv</span></button></div>`;
}
function ligarPlanilhas() {
  $$('[data-pl]').forEach(b => b.onclick = () => plImportar());
  $$('[data-pla]').forEach(b => b.onclick = () => plAbre(+b.dataset.pla));
}

/* ---------- ler o arquivo e achar a tabela de cada aba ---------- */
function plCsv(txt) {
  const linhas = txt.replace(/^﻿/, '').split(/\r?\n/), d = (linhas[0].match(/;/g) || []).length > (linhas[0].match(/,/g) || []).length ? ';' : (linhas[0].includes('\t') ? '\t' : ',');
  const out = []; let campo = '', lin = [], aspas = false; const t = txt.replace(/^﻿/, '');
  for (let i = 0; i < t.length; i++) { const ch = t[i];
    if (aspas) { if (ch === '"') { if (t[i + 1] === '"') { campo += '"'; i++; } else aspas = false; } else campo += ch; }
    else if (ch === '"') aspas = true; else if (ch === d) { lin.push(campo); campo = ''; } else if (ch === '\n' || ch === '\r') { if (ch === '\r' && t[i + 1] === '\n') i++; lin.push(campo); out.push(lin); lin = []; campo = ''; } else campo += ch; }
  if (campo !== '' || lin.length) { lin.push(campo); out.push(lin); }
  return out.map(l => l.map(v => { const s = v.trim(); return s === '' ? null : s; }));
}
async function plLerArquivo(arq) {
  if (/\.csv$/i.test(arq.name) || arq.type === 'text/csv') return [{ nome: arq.name.replace(/\.[^.]+$/, ''), rows: plCsv(await arq.text()) }];
  const X = await lerXlsx(await arq.arrayBuffer()), o = [];
  for (const f of X.folhas) o.push({ nome: f.nome, rows: await X.lerFolha(f) });
  return o;
}
const plNumTxt = s => { if (typeof s !== 'string') return null; const t = s.replace(/R\$|\s| /g, ''); if (!/^-?[\d.,]+$/.test(t) || !/\d/.test(t)) return null;
  const n = t.includes(',') ? +t.replace(/\./g, '').replace(',', '.') : (t.split('.').length > 2 ? +t.replace(/\./g, '') : +t); return isFinite(n) ? n : null; };
const plDataTxt = s => { if (typeof s !== 'string') return ''; let m = s.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/); if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; m = s.trim().match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[0].slice(0, 10) : ''; };
/* a data do Excel (o número de dias desde 1900); com hora (a fração) só quando a coluna é de data */
const plSerial = (v, comHora) => typeof v === 'number' && v > 20000 && v < 73100 && (comHora || Number.isInteger(Math.round(v * 1e6) / 1e6)) ? new Date(Math.round((Math.floor(v) - 25569) * 86400000)).toISOString().slice(0, 10) : '';
const plVazio = v => v == null || (typeof v === 'string' && !v.trim());
/* a tabela de uma aba: o cabeçalho, as colunas (com o tipo) e as linhas */
function plDetecta(rows, nome) {
  const ehTexto = v => typeof v === 'string' && v.trim() && plNumTxt(v) == null && !plDataTxt(v);
  const conta = r => { const L = rows[r] || []; let t = 0, n = 0; L.forEach(v => { if (!plVazio(v)) { n++; if (ehTexto(v)) t++; } }); return [t, n]; };
  const lim = Math.min(rows.length, 30); let maxT = 0; for (let r = 0; r < lim; r++) maxT = Math.max(maxT, conta(r)[0]);
  if (maxT < 1) return null;
  let h = -1; for (let r = 0; r < lim; r++) { const [t, n] = conta(r); if (t >= Math.min(2, maxT) && t >= n * 0.6 && t >= maxT * 0.5) { h = r; break; } }
  if (h < 0) return null;
  const cab = rows[h] || [], ultimaLinha = rows.length, cols = [];
  let larg = cab.length; for (let r = h + 1; r < Math.min(rows.length, h + 400); r++) larg = Math.max(larg, (rows[r] || []).length);
  for (let c = 0; c < larg; c++) { const nomeC = plVazio(cab[c]) ? '' : String(cab[c]).replace(/\s+/g, ' ').trim(); let tem = false;
    for (let r = h + 1; r < ultimaLinha && !tem; r++) if (!plVazio((rows[r] || [])[c])) tem = true;
    if (nomeC || tem) cols.push({ c, nome: nomeC || `Coluna ${cols.length + 1}` }); }
  if (!cols.length) return null;
  /* os nomes repetidos ganham um número ("Valor", "Valor 2") */
  const vistos = {}; cols.forEach(x => { const k = gNorm(x.nome); vistos[k] = (vistos[k] || 0) + 1; if (vistos[k] > 1) x.nome += ' ' + vistos[k]; });
  const nCab = gNorm(cols.map(x => x.nome).join('|')), dados = [];
  for (let r = h + 1; r < ultimaLinha; r++) { const L = rows[r] || []; if (cols.every(x => plVazio(L[x.c]))) continue;
    const prim = cols.map(x => L[x.c]).find(v => !plVazio(v)); if (typeof prim === 'string' && /^(total|subtotal|soma)\b/.test(gNorm(prim))) continue;
    if (gNorm(cols.map(x => plVazio(L[x.c]) ? '' : String(L[x.c]).replace(/\s+/g, ' ').trim()).join('|')) === nCab) continue;
    dados.push({ r, L }); }
  const reData = /data|dia\b|venc|pagamento|emiss|compet|nascimento/, reValor = /valor|total|preco|r\$|custo|pago|receb|saldo|montante|despesa|receita|faturamento|salario|bruto|liquido/, reNum = /qtd|qtde|quant|numero|\bn[ºo°]|^n$|nota|parcela/;
  for (const x of cols) { const vs = dados.map(d => d.L[x.c]).filter(v => !plVazio(v)), nn = gNorm(x.nome); let tipo = 'texto', op = [];
    if (!vs.length) tipo = reData.test(nn) ? 'data' : reValor.test(nn) ? 'valor' : reNum.test(nn) ? 'numero' : 'texto';
    else { const datas = vs.filter(v => plDataTxt(v) || (typeof v === 'number' && plSerial(v, reData.test(nn)) && (reData.test(nn) || v > 36000))).length, nums = vs.filter(v => typeof v === 'number' || plNumTxt(v) != null).length;
      if (datas >= vs.length * 0.8 && (reData.test(nn) || vs.some(v => plDataTxt(v)) || !reValor.test(nn))) tipo = 'data';
      else if (nums >= vs.length * 0.8) { const fr = vs.some(v => { const n = typeof v === 'number' ? v : plNumTxt(v); return Math.abs(n - Math.round(n)) > 1e-9; }); tipo = reValor.test(nn) || (fr && !reNum.test(nn)) ? 'valor' : 'numero'; }
      else if (vs.every(v => typeof v === 'boolean')) { tipo = 'lista'; op = ['Sim', 'Não']; }
      else { const freq = {}; vs.forEach(v => { const s = String(v).trim(); freq[s] = (freq[s] || 0) + 1; }); const k = Object.keys(freq);
        if (vs.length >= 4 && k.length <= 12 && k.length <= vs.length / 2 && k.every(s => s.length <= 40)) { tipo = 'lista'; op = k.sort((a, b) => freq[b] - freq[a]); } } }
    x.tipo = tipo; x.opcoes = op; }
  return { nome, h, cols, dados };
}
/* o valor de uma célula no formato que a planilha do sistema guarda */
function plValor(v, tipo) {
  if (plVazio(v)) return '';
  if (tipo === 'data') return plDataTxt(v) || plSerial(v, true) || '';
  if (tipo === 'valor' || tipo === 'numero') return typeof v === 'number' ? v : (plNumTxt(v) != null ? plNumTxt(v) : String(v));
  if (typeof v === 'boolean') return v ? 'Sim' : 'Não';
  if (typeof v === 'number') return String(Math.round(v * 1e6) / 1e6).replace('.', ',');
  return String(v).replace(/\s+/g, ' ').trim();
}
const plColsApi = cols => cols.map((x, i) => ({ id: 'c' + (i + 1), nome: x.nome, tipo: x.tipo, opcoes: x.tipo === 'lista' ? x.opcoes : [] }));
const plLinhasApi = (T, cols) => T.dados.map(d => { const o = { _l: d.r + 1 }; cols.forEach((x, i) => { const v = plValor(d.L[x.c], x.tipo); if (v !== '') o['c' + (i + 1)] = v; }); return o; });
/* manda as linhas aos pedaços (o servidor recebe até ~200 KB por vez) */
async function plEnviar(acaoPrimeira, base, linhas, progresso) {
  let i = 0, foi = 0, erros = 0, ex = [], resp = null; const pedaco = () => { const o = []; let tam = 0; while (i < linhas.length && o.length < 1500) { const j = JSON.stringify(linhas[i]).length; if (o.length && tam + j > 140000) break; o.push(linhas[i++]); tam += j; } return o; };
  let primeiro = pedaco(); resp = await api(acaoPrimeira, { ...base, linhas: primeiro }); foi += resp.entrou; erros += resp.erros; ex = ex.concat(resp.exemplos || []); progresso(foi + erros);
  const id = resp.planilha ? resp.planilha.id : base.id;
  while (i < linhas.length) { const p = pedaco(), r = await api('pl_linhas_add', { id, linhas: p }); foi += r.entrou; erros += r.erros; ex = ex.concat(r.exemplos || []); progresso(foi + erros); }
  return { id, foi, erros, ex };
}

/* ---------- importar: escolher o arquivo, ver como vai ficar, importar ---------- */
function plImportar(alvo) {
  let tabelas = [], arqNome = '';
  const f = gFolha(alvo ? `Importar mais linhas em "${alvo.planilha.nome}"` : 'Importar planilha', `<p class="sub" style="margin:0 0 12px">${alvo ? 'Escolha a planilha (.xlsx ou .csv). As colunas são casadas pelo nome; as linhas entram no fim.' : 'Escolha a planilha (.xlsx ou .csv). O sistema acha as colunas e copia as linhas; cada aba vira uma planilha aqui dentro.'}</p>
      <input class="inp g-arq" type="file" id="pl-arq" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"><div id="pl-prev" class="pl-prev"></div>`, {
    ok: 'Importar', fazer: async F => {
      const marcadas = tabelas.filter((T, i) => { const c = $(`#pl-usa-${i}`, F); return c ? c.checked : true; }); if (!marcadas.length) throw new Error(tabelas.length ? 'Marque pelo menos uma aba.' : 'Escolha a planilha primeiro.');
      const res = $('#pl-prev', F), tot = marcadas.reduce((t, T) => t + T.dados.length, 0) || 1; let feito = 0, ult = 0, soma = { foi: 0, erros: 0, ex: [] };
      const prog = n => { res.innerHTML = `<div class="barra"><i style="width:${Math.round((feito + n) / tot * 100)}%"></i></div><p class="sub">Copiando as linhas… ${int(feito + n)} de ${int(tot)}</p>`; };
      for (const T of marcadas) { const i = tabelas.indexOf(T);
        if (alvo) { const linhas = T.dados.map(d => { const o = { _l: d.r + 1 }; T.casa.forEach(([c, colId, tipo]) => { const v = plValor(d.L[c], tipo); if (v !== '') o[colId] = v; }); return o; });
          const r = await plEnviar('pl_linhas_add', { id: alvo.planilha.id }, linhas, prog); soma.foi += r.foi; soma.erros += r.erros; soma.ex = soma.ex.concat(r.ex); ult = alvo.planilha.id; }
        else { $$(`[data-plc^="${i}-"]`, F).forEach(s => { const [, k] = s.dataset.plc.split('-'); T.cols[+k].tipo = s.value; }); const nm = $(`#pl-nome-${i}`, F); const cols = T.cols;
          const r = await plEnviar('pl_criar', { nome: nm ? nm.value.trim() : T.nome, arquivo: arqNome, colunas: plColsApi(cols) }, plLinhasApi(T, cols), prog); soma.foi += r.foi; soma.erros += r.erros; soma.ex = soma.ex.concat(r.ex); ult = r.id; }
        feito += T.dados.length; }
      aviso(`${alvo ? 'Linhas importadas' : marcadas.length > 1 ? `${marcadas.length} planilhas criadas` : 'Planilha criada'} ✓ ${int(soma.foi)} linhas${soma.erros ? ` · ${int(soma.erros)} não entraram` : ''}`);
      if (soma.erros) setTimeout(() => alert(`${int(soma.erros)} linhas não entraram. Exemplos:\n` + soma.ex.slice(0, 8).map(x => `linha ${x.linha}: ${x.erro}`).join('\n')), 300);
      PL.lista = null; if (alvo) { PL.atual = null; planDesenhar(); } else if (marcadas.length === 1) plAbre(ult); else { PL.id = 0; planDesenhar(); } } });
  $('#pl-arq', f).onchange = async e => { const a = e.target.files[0], res = $('#pl-prev', f); tabelas = []; if (!a) return; arqNome = a.name; res.innerHTML = '<p class="sub">Lendo a planilha…</p>';
    try { const abas = await plLerArquivo(a); tabelas = abas.map(x => plDetecta(x.rows, abas.length > 1 ? x.nome : a.name.replace(/\.[^.]+$/, ''))).filter(Boolean);
      if (!tabelas.length) { res.innerHTML = '<p class="erro">Não achei nenhuma tabela nesta planilha (uma linha com os nomes das colunas e as linhas embaixo).</p>'; return; }
      if (alvo) { const C = alvo.planilha.colunas; tabelas.forEach(T => { T.casa = []; T.cols.forEach(x => { const c = C.find(y => gNorm(y.nome) === gNorm(x.nome)); if (c) T.casa.push([x.c, c.id, c.tipo]); }); });
        tabelas = tabelas.filter(T => T.casa.length).sort((a2, b2) => b2.casa.length - a2.casa.length).slice(0, 1);
        if (!tabelas.length) { res.innerHTML = '<p class="erro">Nenhuma coluna desta planilha tem o mesmo nome das colunas daqui.</p>'; return; }
        const T = tabelas[0]; res.innerHTML = `<p style="margin:12px 0 4px"><b>${int(T.dados.length)}</b> ${T.dados.length === 1 ? 'linha' : 'linhas'} para entrar · ${int(T.casa.length)} de ${int(C.filter(c => !c.oculta).length)} colunas casadas pelo nome: ${T.casa.map(x => esc(C.find(c => c.id === x[1]).nome)).join(', ')}</p>`; return; }
      res.innerHTML = tabelas.map((T, i) => `<div class="pl-tab">${tabelas.length > 1 ? `<label class="chk" style="margin:0 0 6px"><input type="checkbox" id="pl-usa-${i}" ${T.dados.length || T.cols.length > 1 ? 'checked' : ''}> Importar esta aba</label>` : ''}
          <label class="lbl" for="pl-nome-${i}">Nome da planilha no sistema</label><input class="inp" id="pl-nome-${i}" value="${esc(T.nome)}">
          <p class="sub" style="margin:8px 0 6px">${int(T.dados.length)} ${T.dados.length === 1 ? 'linha' : 'linhas'} · ${int(T.cols.length)} colunas (achei o cabeçalho na linha ${T.h + 1}). Confira o tipo de cada coluna:</p>
          <div class="pl-tipos">${T.cols.map((x, k) => `<div><span title="${esc(x.nome)}">${esc(x.nome)}</span><select class="inp" data-plc="${i}-${k}">${Object.entries(PL_TIPOS).map(([tp, nm]) => `<option value="${tp}"${tp === x.tipo ? ' selected' : ''}>${nm}</option>`).join('')}</select></div>`).join('')}</div>
          ${T.dados.length ? `<div class="rolar"><table class="pl-mini"><thead><tr>${T.cols.map(x => `<th>${esc(x.nome)}</th>`).join('')}</tr></thead><tbody>${T.dados.slice(0, 4).map(d => `<tr>${T.cols.map(x => `<td>${esc(plMostra(plValor(d.L[x.c], x.tipo), x.tipo))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>${T.dados.length > 4 ? `<p class="sub">… e mais ${int(T.dados.length - 4)} linhas.</p>` : ''}` : '<p class="sub">Só o cabeçalho (modelo vazio): as linhas vocês lançam aqui.</p>'}</div>`).join(''); }
    catch (x) { res.innerHTML = `<p class="erro">${esc(x.message)}</p>`; } };
}

/* ---------- uma planilha ---------- */
const plMostra = (v, tipo) => v === '' || v == null ? '' : tipo === 'data' ? (/^\d{4}-\d{2}-\d{2}$/.test(v) ? dataBR(v) : String(v)) : tipo === 'valor' ? (typeof v === 'number' ? brl(v) : String(v)) : tipo === 'numero' ? (typeof v === 'number' ? v.toLocaleString('pt-BR', { maximumFractionDigits: 4 }) : String(v)) : String(v);
function plFiltradas() {
  const A = PL.atual; if (!A) return []; const cols = A.planilha.colunas, cd = cols.find(c => c.tipo === 'data' && !c.oculta), b = gNorm(PL.busca);
  let L = A.linhas.filter(l => (!PL.mes || !cd || String(l.d[cd.id] || '').slice(0, 7) === PL.mes) && (!b || cols.some(c => gNorm(plMostra(l.d[c.id], c.tipo)).includes(b))));
  const o = PL.ordem || (cd ? { id: cd.id, dir: -1 } : null);
  if (o) { const c = cols.find(x => x.id === o.id); if (c) L = L.slice().sort((x, y) => { const a = x.d[c.id], z = y.d[c.id]; if (a == null || a === '') return 1; if (z == null || z === '') return -1; return (typeof a === 'number' && typeof z === 'number' ? a - z : String(a).localeCompare(String(z), 'pt-BR', { numeric: true })) * o.dir || (x.id - y.id); }); }
  return L;
}
function telaPlanilha() {
  const A = PL.atual;
  const volta = '<button class="link pl-volta" data-plv>‹ Todas as planilhas</button>';
  if (!A) return volta + '<div class="vazio">Carregando…</div>';
  const P = A.planilha, cols = P.colunas.filter(c => !c.oculta), cd = cols.find(c => c.tipo === 'data'), L = plFiltradas(), nums = cols.filter(c => c.tipo === 'valor' || c.tipo === 'numero');
  const meses = cd ? [...new Set(A.linhas.map(l => String(l.d[cd.id] || '').slice(0, 7)).filter(m => /^\d{4}-\d{2}$/.test(m)))].sort().reverse() : [];
  const o = PL.ordem || (cd ? { id: cd.id, dir: -1 } : null), seta = c => o && o.id === c.id ? (o.dir > 0 ? ' ↑' : ' ↓') : '';
  const tot = c => L.reduce((t, l) => t + (typeof l.d[c.id] === 'number' ? l.d[c.id] : 0), 0);
  return volta + `<div class="topo"><div><h1>${esc(P.nome)}</h1><div class="sub">${int(A.linhas.length)} ${A.linhas.length === 1 ? 'linha' : 'linhas'}${P.arquivo ? ` · importada de ${esc(P.arquivo)}` : ''} · criada por ${esc(P.criado_por)} em ${esc(quandoBR(P.criado_em))}</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" data-plb="colunas">Colunas</button><button class="btn btn-ghost" data-plb="mais">Importar mais linhas</button><button class="btn btn-ghost" data-plb="excel">Exportar Excel</button><button class="btn btn-brand g-novo" data-plb="nova">+ Nova linha</button></div></div>
    <div class="filtros"><div class="chips"><input class="chip g-busca" id="pl-busca" type="search" placeholder="Buscar…" value="${esc(PL.busca)}" aria-label="Buscar">
      ${meses.length ? `<select class="chip ${PL.mes ? 'on' : ''}" id="pl-mes" aria-label="Mês"><option value="">Todos os meses</option>${meses.map(m => `<option value="${m}"${m === PL.mes ? ' selected' : ''}>${esc(gMesNome(m))}</option>`).join('')}</select>` : ''}</div></div>
    ${nums.length ? `<div class="grade pl-kpis">${kpi(PL.mes || PL.busca ? 'Linhas no filtro' : 'Linhas', int(L.length), PL.mes ? esc(gMesNome(PL.mes)) : PL.busca ? 'com a busca' : 'na planilha')}${nums.slice(0, 3).map(c => kpi('Total de ' + esc(c.nome), c.tipo === 'valor' ? brl(tot(c)) : tot(c).toLocaleString('pt-BR', { maximumFractionDigits: 2 }), PL.mes || PL.busca ? 'das linhas do filtro' : 'de todas as linhas')).join('')}</div>` : ''}
    <div class="cartao">${!A.linhas.length ? `<div class="vazio">A planilha ainda não tem linhas.<br><button class="btn btn-ghost" style="margin-top:12px" data-plb="nova">+ Nova linha</button></div>`
      : !L.length ? '<div class="vazio">Nenhuma linha com este filtro.</div>'
      : `<p class="expl">Toque numa linha para corrigir. Toque no nome da coluna para ordenar.</p><div class="rolar"><table class="g-lanc pl-tab-dados"><thead><tr>${cols.map(c => `<th class="${c.tipo === 'valor' || c.tipo === 'numero' ? 'v' : ''}" data-plo="${c.id}" title="Ordenar">${esc(c.nome)}${seta(c)}</th>`).join('')}</tr></thead>
        <tbody>${L.slice(0, PL.mostra).map(l => `<tr data-pll="${l.id}" tabindex="0" title="Corrigir">${cols.map(c => `<td class="${c.tipo === 'valor' || c.tipo === 'numero' ? 'v' : ''}">${esc(plMostra(l.d[c.id], c.tipo))}</td>`).join('')}</tr>`).join('')}</tbody>
        ${nums.length ? `<tfoot><tr>${cols.map((c, i) => `<td class="${c.tipo === 'valor' || c.tipo === 'numero' ? 'v' : ''}">${c.tipo === 'valor' ? brl(tot(c)) : c.tipo === 'numero' ? tot(c).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) : i === 0 ? `Total · ${int(L.length)}` : ''}</td>`).join('')}</tr></tfoot>` : ''}</table></div>
        ${L.length > PL.mostra ? `<button class="btn btn-ghost" style="margin-top:12px" data-plb="mostrar">Mostrar mais (${int(L.length - PL.mostra)})</button>` : ''}`}</div>`;
}
function ligarPlanilha() {
  const v = $('[data-plv]'); if (v) v.onclick = () => { PL.id = 0; PL.atual = null; PL.lista = null; planDesenhar(); };
  if (!PL.atual) return;
  $$('[data-plb]').forEach(b => b.onclick = () => ({ nova: () => plFormLinha(), mais: () => plImportar(PL.atual), excel: () => plExcel(), colunas: () => plFolhaColunas(), mostrar: () => { PL.mostra += 300; planDesenhar(); } })[b.dataset.plb]());
  const bu = $('#pl-busca'); if (bu) { let t0 = 0; bu.oninput = () => { clearTimeout(t0); t0 = setTimeout(() => { PL.busca = bu.value.trim(); PL.mostra = 300; const pos = bu.selectionStart; planDesenhar(); const b2 = $('#pl-busca'); if (b2) { b2.focus(); b2.setSelectionRange(pos, pos); } }, 250); }; }
  const me = $('#pl-mes'); if (me) me.onchange = () => { PL.mes = me.value; PL.mostra = 300; planDesenhar(); };
  $$('[data-plo]').forEach(th => th.onclick = () => { const id = th.dataset.plo, o = PL.ordem; PL.ordem = { id, dir: o && o.id === id ? -o.dir : 1 }; planDesenhar(); });
  $$('tr[data-pll]').forEach(tr => { const abre = () => { const l = PL.atual.linhas.find(x => x.id === +tr.dataset.pll); if (l) plFormLinha(l); }; tr.onclick = abre; tr.onkeydown = e => { if (e.key === 'Enter') abre(); }; });
}
/* os campos de uma linha: cada coluna com o campo certo (data, valor, número, lista, texto) */
function plFormLinha(l) {
  const P = PL.atual.planilha, cols = P.colunas.filter(c => !c.oculta), ult = PL.ultimaLinha || {};
  const campo = c => { const id = 'pl-' + c.id, v = l ? l.d[c.id] : (c.tipo === 'data' ? (ult[c.id] || gHoje()) : c.tipo === 'lista' ? (ult[c.id] || '') : '');
    const rot = `${esc(c.nome)} <small>${PL_TIPOS[c.tipo]}</small>`;
    if (c.tipo === 'data') return gCampo(id, rot, gInp(id, v || '', 'type="date"'));
    if (c.tipo === 'valor') return gCampo(id, rot, gInp(id, typeof v === 'number' ? gValor(v) : (v || ''), 'inputmode="decimal" placeholder="0,00" data-limpa'));
    if (c.tipo === 'numero') return gCampo(id, rot, gInp(id, typeof v === 'number' ? String(v).replace('.', ',') : (v || ''), 'inputmode="decimal" data-limpa'));
    if (c.tipo === 'lista') return gCampo(id, rot, gInp(id, v || '', `list="${id}-dl" autocomplete="off" placeholder="Escolha ou escreva"`) + gDl(id + '-dl', c.opcoes));
    return gCampo(id, rot, gInp(id, v || '', 'data-limpa'), cols.length > 3 && /obs|descri|hist|coment|detalhe/.test(gNorm(c.nome))); };
  const quem = l ? `<p class="sub" style="margin:10px 0 0">${l.origem === 'importada' ? 'Veio da planilha importada' : 'Lançada'} por ${esc(l.criado_por || '—')} em ${esc(quandoBR(l.criado_em))}${l.alterado_por ? ` · corrigida por ${esc(l.alterado_por)} em ${esc(quandoBR(l.alterado_em))}` : ''}</p>` : '';
  gFolha(l ? 'Corrigir linha' : `Nova linha · ${P.nome}`, `<div class="g-grid">${cols.map(campo).join('')}</div>${quem}`, {
    ok: l ? 'Salvar' : 'Lançar', outra: l ? '' : 'Lançar e mais uma', remover: l ? 'Tirar esta linha' : '',
    fazer: async F => { const dados = {}; cols.forEach(c => { const e = $('#pl-' + c.id, F); if (e && e.value.trim() !== '') dados[c.id] = e.value.trim(); });
      if (!Object.keys(dados).length) throw new Error('Preencha pelo menos um campo.');
      const r = await api('pl_linha_salvar', { planilha_id: P.id, id: l ? l.id : 0, dados }); P.colunas = r.colunas || P.colunas;
      if (l) Object.assign(l, r.linha); else { PL.atual.linhas.push(r.linha); PL.ultimaLinha = r.linha.d; }
      PL.lista = null; planDesenhar(); },
    aoRemover: async () => { await api('pl_linha_remover', { planilha_id: P.id, id: l.id }); PL.atual.linhas = PL.atual.linhas.filter(x => x.id !== l.id); PL.lista = null; aviso('Linha tirada ✓'); planDesenhar(); } });
}
/* as colunas: renomear, o tipo, esconder, a ordem, uma coluna nova; e o nome da planilha */
function plFolhaColunas() {
  const P = PL.atual.planilha; let C = P.colunas.map(c => ({ ...c, opcoes: (c.opcoes || []).slice() }));
  const linhas = () => C.map((c, i) => `<div class="pl-col" data-i="${i}"><input class="inp" data-pcn="${i}" value="${esc(c.nome)}" aria-label="Nome da coluna"><select class="inp" data-pct="${i}" aria-label="Tipo">${Object.entries(PL_TIPOS).map(([t, n]) => `<option value="${t}"${t === c.tipo ? ' selected' : ''}>${n}</option>`).join('')}</select>
      <label class="chk" title="Esconder a coluna (o que foi lançado continua guardado)"><input type="checkbox" data-pco="${i}" ${c.oculta ? '' : 'checked'}> Mostrar</label><button type="button" class="chip" data-pcu="${i}" ${i ? '' : 'disabled'} aria-label="Subir">↑</button><button type="button" class="chip" data-pcd="${i}" ${i < C.length - 1 ? '' : 'disabled'} aria-label="Descer">↓</button></div>`).join('');
  const f = gFolha('Colunas da planilha', `<label class="lbl" for="pl-pnome">Nome da planilha</label><input class="inp" id="pl-pnome" value="${esc(P.nome)}"><p class="sub" style="margin:14px 0 6px">As colunas (o nome, o tipo, mostrar ou esconder, a ordem):</p><div id="pl-cols">${linhas()}</div>
      <button type="button" class="btn btn-ghost" id="pl-addcol" style="margin-top:10px">+ Coluna nova</button><p class="sub" style="margin:10px 0 0">Mudar o tipo não apaga nada: o que já foi lançado continua guardado.</p>`, {
    ok: 'Salvar', remover: 'Tirar esta planilha',
    fazer: async F => { lerC(F); const r = await api('pl_salvar', { id: P.id, nome: $('#pl-pnome', F).value, colunas: C }); PL.atual.planilha = { ...PL.atual.planilha, ...r.planilha }; PL.lista = null; aviso('Planilha salva ✓'); planDesenhar(); },
    aoRemover: async () => { await api('pl_remover', { id: P.id }); aviso('Planilha tirada ✓'); PL.id = 0; PL.atual = null; PL.lista = null; planDesenhar(); } });
  const lerC = F => { $$('[data-pcn]', F).forEach(e => { C[+e.dataset.pcn].nome = e.value; }); $$('[data-pct]', F).forEach(e => { C[+e.dataset.pct].tipo = e.value; }); $$('[data-pco]', F).forEach(e => { C[+e.dataset.pco].oculta = !e.checked; }); };
  const liga = () => { $$('[data-pcu]', f).forEach(b => b.onclick = () => { lerC(f); const i = +b.dataset.pcu; [C[i - 1], C[i]] = [C[i], C[i - 1]]; $('#pl-cols', f).innerHTML = linhas(); liga(); });
    $$('[data-pcd]', f).forEach(b => b.onclick = () => { lerC(f); const i = +b.dataset.pcd; [C[i + 1], C[i]] = [C[i], C[i + 1]]; $('#pl-cols', f).innerHTML = linhas(); liga(); }); };
  liga();
  $('#pl-addcol', f).onclick = () => { lerC(f); const n = Math.max(0, ...C.map(c => +c.id.slice(1))) + 1; C.push({ id: 'c' + n, nome: 'Coluna ' + (C.length + 1), tipo: 'texto', opcoes: [], oculta: false }); $('#pl-cols', f).innerHTML = linhas(); liga(); const u = $$('[data-pcn]', f).pop(); if (u) { u.focus(); u.select(); } };
}
function plExcel() {
  const P = PL.atual.planilha, cols = P.colunas.filter(c => !c.oculta), L = plFiltradas(), tipoX = { data: 'data', valor: 'brl', numero: 'num', texto: 'txt', lista: 'txt' };
  const tem = cols.some(c => c.tipo === 'valor' || c.tipo === 'numero');
  const aba = { nome: P.nome.replace(/[\\/?*[\]:]/g, ' ').slice(0, 31) || 'Planilha', cols: cols.map(c => [c.nome, Math.min(40, Math.max(10, c.nome.length + 4, c.tipo === 'texto' ? 22 : 12)), tipoX[c.tipo]]),
    linhas: L.map(l => cols.map(c => { const v = l.d[c.id]; return v == null ? '' : v; })),
    total: tem ? cols.map((c, i) => c.tipo === 'valor' || c.tipo === 'numero' ? L.reduce((t, l) => t + (typeof l.d[c.id] === 'number' ? l.d[c.id] : 0), 0) : i === 0 ? 'Total' : '') : null };
  gBaixa(gXlsx([aba]), `${gNorm(P.nome).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'planilha'}${PL.mes ? '-' + PL.mes : ''}.xlsx`); aviso('Planilha baixada ✓');
}
